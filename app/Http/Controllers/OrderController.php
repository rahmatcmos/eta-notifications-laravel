<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Order;
use Twilio\Rest\Client;
use Log;

class OrderController extends Controller
{
    public function index()
    {
        return view('index', ['orders' => Order::all()]);
    }

    public function show($id)
    {
        return view('show', ['order' => Order::find($id)]);
    }

    public function pickup(Client $client, Request $request, $id)
    {
        $order = Order::find($id);
        $order->status = 'Shipped';
        $order->notification_status = 'queued';
        $order->save();

        $callbackUrl = str_replace('/pickup', '', $request->url()) . '/notification/status/update';
        $this->sendMessage(
            $client,
            $order->phone_number,
            'Your laundry is done and on its way to you!',
            $callbackUrl
        );

        return redirect()->route('order.show', ['id' => $order->id]);
    }

    public function deliver(Client $client, Request $request, $id)
    {
        $order = Order::find($id);
        $order->status = 'Delivered';
        $order->notification_status = 'queued';
        $order->save();

        $callbackUrl = str_replace('/deliver', '', $request->url()) . '/notification/status/update';
        $this->sendMessage(
            $client,
            $order->phone_number,
            'Your laundry is arriving now.',
            $callbackUrl
        );

        return redirect()->route('order.index');
    }

    public function notificationStatus(Request $request, $id)
    {
        $order = Order::find($id);
        $order->notification_status = $request->input('MessageStatus');
        $order->save();
    }

    private function sendMessage($client, $to, $messageBody, $callbackUrl)
    {
        $twilioNumber = config('services.twilio')['number'];
        try {
            $client->messages->create(
                $to, // Text any number
                [
                    'from' => $twilioNumber, // From a Twilio number in your account
                    'body' => $messageBody,
                    'statusCallback' => $callbackUrl
                ]
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
